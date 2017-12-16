#!/usr/bin/env python
# encoding: utf-8
"""
Based on the work by cave, see submodule.
"""

import argparse
import config
import json
import requests


def get_uuid():
    """ 
    find out ZONE UUID from domain
    Info on domain "DOMAIN"
    GET /domains/<DOMAIN>:
        
    """
    url = config.api_endpoint + '/domains/' + config.domain
    u = requests.get(url, headers={"X-Api-Key": config.api_secret})
    json_object = json.loads(u._content)
    if u.status_code == 200:
        return json_object['zone_uuid']
    else:
        print 'Error: HTTP Status Code ', u.status_code, 'when trying to get Zone UUID'
        print json_object['message']
        exit(11)


def get_dns_ip(uuid):
    """ find out IP from first Subdomain DNS-Record
    List all records with name "NAME" and type "TYPE" in the zone UUID
    GET /zones/<UUID>/records/<NAME>/<TYPE>:
    
    The first subdomain from config.subdomain will be used to get   
    the actual DNS Record IP
    """

    url = config.api_endpoint + '/zones/' + uuid + '/records/' + config.subdomains[0] + '/A'
    headers = {"X-Api-Key": config.api_secret}
    u = requests.get(url, headers=headers)
    json_object = json.loads(u._content)
    if u.status_code == 200:
        return json_object['rrset_values'][0].encode('ascii', 'ignore').strip('\n')
    else:
        print 'Error: HTTP Status Code ', u.status_code, 'when trying to get IP from subdomain', config.subdomains[0]
        print json_object['message']
        exit(12)


def update_records(uuid, dyn_ip, subdomain):
    """ update DNS Records for Subdomains 
        Change the "NAME"/"TYPE" record from the zone UUID
        PUT /zones/<UUID>/records/<NAME>/<TYPE>:
        curl -X PUT -H "Content-Type: application/json" \
                    -H 'X-Api-Key: XXX' \
                    -d '{"rrset_ttl": 10800,
                         "rrset_values": ["<VALUE>"]}' \
                    https://dns.beta.gandi.net/api/v5/zones/<UUID>/records/<NAME>/<TYPE>
    """
    url = config.api_endpoint + '/zones/' + uuid + '/records/' + subdomain + '/A'
    payload = {"rrset_ttl": config.ttl, "rrset_values": [dyn_ip]}
    headers = {"Content-Type": "application/json", "X-Api-Key": config.api_secret}
    u = requests.put(url, data=json.dumps(payload), headers=headers)
    json_object = json.loads(u._content)

    if u.status_code == 201:
        return True
    else:
        print 'Error: HTTP Status Code ', u.status_code, 'when trying to update IP from subdomain', subdomain
        print  json_object['message']
        exit(10)


def main(force_update, verbosity):
    if verbosity:
        print "verbosity turned on - not implemented by now"

    # get zone ID from Account
    uuid = get_uuid()

    # compare dyn_ip and DNS IP
    dyn_ip = config.ip
    dns_ip = get_dns_ip(uuid)

    if force_update:
        print "Going to update/create the DNS Records for the subdomains"
        for sub in config.subdomains:
            update_records(uuid, dyn_ip, sub)
    else:
        if dyn_ip != dns_ip:
            for sub in config.subdomains:
                update_records(uuid, dyn_ip, sub)


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument('-v', '--verbose', help="increase output verbosity", action="store_true")
    parser.add_argument('-f', '--force', help="force an update/create", action="store_true")
    args = parser.parse_args()

    main(args.force, args.verbose)
